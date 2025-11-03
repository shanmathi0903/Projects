package dbFolder;

import beanFolder.Bus;
import java.util.ArrayList;
import java.util.HashMap;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.io.BufferedWriter;

public class BusDb{
	ArrayList<Bus> bus=new ArrayList<>();
	
	int id=1;
	public void addBusDetails(String name,String regNo,String source,String destination,ArrayList<String> route,ArrayList<Float> price,int seater,int sleeper,float sleeperPrice,boolean hasAc){
		bus.add(new Bus(id,name,regNo,source,destination,route,price,seater,sleeper,sleeperPrice,hasAc));
		
		try(BufferedWriter bw=new BufferedWriter(new FileWriter("BusDetails",true))){
			bw.write(id+","+name+","+regNo+","+source+","+destination+","+route+","+price+","+seater+","+sleeper+","+sleeperPrice+","+hasAc);
			bw.newLine();
		}
		catch(IOException e){
			System.out.println(e.getMessage());
		}
		
		System.out.println("Bus Added Successfully");
		id++;
	}
	
	public void displayBusDetails(){
		System.out.printf("%-5s %-15s %-10s %-10s %-12s %-20s %-18s %-18s %-5s%n",
			"ID","BusName","RegNo","Source","Destination","Route","Available Seater","Available Sleeper","AC");
		System.out.println("------------------------------------------------------------------------------------------------------");
		for(Bus b:bus){
			String route;
			if(b.getRoute()==null || b.getRoute().isEmpty()){
			   route="Point to Point";
			}else{
			   route=b.getRoute().toString();
			}
			System.out.printf("%-5d %-15s %-10s %-10s %-12s %-20s %-18d %-18d %-5s%n",
				b.getBusId(),b.getBusName(),b.getRegNo(),b.getSource(),b.getDestination(),route,b.getAvailableSeater(),b.getAvailableSleeper(),b.isHasAc()?"Yes":"No");
		}
	}

	public void searchBus(String source,String destination){
		System.out.printf("%-5s %-15s %-10s %-10s %-12s %-20s %-18s %-18s %-5s%n",
			"ID","BusName","RegNo","Source","Destination","Route","Available Seater","Available Sleeper","AC");
		System.out.println("------------------------------------------------------------------------------------------------------");
		
		for(Bus b:bus){
			ArrayList<String> route=new ArrayList<>();
			route.add(b.getSource());
			route.addAll(b.getRoute());
			route.add(b.getDestination());
			if(route.contains(source) && route.contains(destination)){
				System.out.printf("%-5d %-15s %-10s %-10s %-12s %-20s %-18d %-18d %-5s%n",
					b.getBusId(),b.getBusName(),b.getRegNo(),b.getSource(),b.getDestination(),b.getRoute(),b.getAvailableSeater(),b.getAvailableSleeper(),b.isHasAc()?"Yes":"No");
			}
		}
	}
	
	public int getAvailableSeater(String busName,String regNo){
		int seats=0;
		for(Bus b:bus){
			if(busName.equals(b.getBusName()) && regNo.equals(b.getRegNo())){
				seats=b.getAvailableSeater();
			}
		}	
		return seats;
	}
	
	public int getAvailableSleeper(String busName,String regNo){
		int seats=0;
		for(Bus b:bus){
			if(busName.equals(b.getBusName()) && regNo.equals(b.getRegNo())){
				seats=b.getAvailableSleeper();
			}
		}	
		return seats;
	}
	
	public void displaySeatAvailability(String busName,String regNo,String date){
		for(Bus b:bus){
			if(busName.equals(b.getBusName())&&regNo.equals(b.getRegNo())){
				ArrayList<Integer> bookedSeater=b.getSeaterSeats().getOrDefault(date,new ArrayList<>());
				ArrayList<Integer> bookedSleeper=b.getSleeperSeats().getOrDefault(date,new ArrayList<>());

				System.out.println("\nSeat availability for "+date+":");

				System.out.println("\nSeater Seats:");
				for(int i=1;i<=b.getSeater();i++){
					System.out.println(i+" "+(bookedSeater.contains(i)?"Booked":"Available"));
				}

				System.out.println("\nSleeper Seats:");
				for(int i=1;i<=b.getSleeper();i++){
					System.out.println(i+" "+(bookedSleeper.contains(i)?"Booked":"Available"));
				}
				return;
			}
		}
		System.out.println("Bus not found!");
	}


	public boolean bookBus(String busName,String regNo,String date,String start,String end,int[] siSeats,int[] slSeats){
		for(Bus b:bus){
			if(busName.equals(b.getBusName())&&regNo.equals(b.getRegNo())){
				HashMap<String,ArrayList<Integer>> seaterMap=b.getSeaterSeats();
				HashMap<String,ArrayList<Integer>> sleeperMap=b.getSleeperSeats();

				ArrayList<Integer> bookedSeaters=seaterMap.getOrDefault(date,new ArrayList<>());
				ArrayList<Integer> bookedSleepers=sleeperMap.getOrDefault(date,new ArrayList<>());

				for(int s:siSeats){
					if(s<=0||s>b.getSeater()||bookedSeaters.contains(s)){
						System.out.println("Seater seat "+s+" is already booked or invalid on "+date+"!");
						return false;
					}
				}
				for(int s:slSeats){
					if(s<=0||s>b.getSleeper()||bookedSleepers.contains(s)){
						System.out.println("Sleeper seat "+s+" is already booked or invalid on "+date+"!");
						return false;
					}
				}

				for(int s:siSeats){
					bookedSeaters.add(s);
				}
				for(int s:slSeats){
					bookedSleepers.add(s);
				}
				seaterMap.put(date,bookedSeaters);
				sleeperMap.put(date,bookedSleepers);
				
				b.setSeaterSeats(seaterMap);
				b.setSleeperSeats(sleeperMap);

				float fare=calculateFare(b,start,end);
				System.out.println("Cost from "+start+" to "+end+" : "+fare);
				float totalfare=0;
				totalfare+=fare*siSeats.length;
				totalfare+=(fare*slSeats.length)+(slSeats.length*b.getSleeperPrice());

				System.out.println("Booking successful for date "+date+"! Total fare: Rs "+totalfare);
				return true;
			}
		}
		System.out.println("No bus found with given details!");
		return false;
	}

	
	public float calculateFare(Bus b,String start,String end){
		ArrayList<String> route=new ArrayList<>();
		route.add(b.getSource());
		route.addAll(b.getRoute());
		route.add(b.getDestination());
		ArrayList<Float> prices=b.getPrice();
		int startIndex=route.indexOf(start);
		int endIndex=route.indexOf(end);
		if(startIndex>=endIndex){
			System.out.println("Invalid route entered!");
			return 0;
		}
		float totalFare=0;
		for(int i=startIndex;i<endIndex;i++){
			totalFare+=prices.get(i);
		}
		return totalFare;
	}

	public void cancelBusTicket(String busName,String regNo,String date,ArrayList<Integer> siSeats,ArrayList<Integer> slSeats){
		for(Bus b:bus){
			if(busName.equals(b.getBusName())&&regNo.equals(b.getRegNo())){
				HashMap<String,ArrayList<Integer>> seaterMap=b.getSeaterSeats();
				HashMap<String,ArrayList<Integer>> sleeperMap=b.getSleeperSeats();
				
				ArrayList<Integer> bookedSeaters=seaterMap.get(date);
				ArrayList<Integer> bookedSleepers=sleeperMap.get(date);
				
				bookedSeaters.removeAll(siSeats);
				bookedSleepers.removeAll(slSeats);
				
				seaterMap.put(date,bookedSeaters);
				sleeperMap.put(date,bookedSleepers);
				
				b.setSeaterSeats(seaterMap);
				b.setSleeperSeats(sleeperMap);
				
				System.out.println("Cancellation successful for bus: "+busName+" ("+date+")");
				System.out.println("Canceled Seater Seats: "+siSeats);
				System.out.println("Canceled Sleeper Seats: "+slSeats);
				return;
			}
		}
		System.out.println("No bus found with given details!");
	}

}
	